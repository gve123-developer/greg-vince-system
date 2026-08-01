import { Product, Transaction } from '@/app/App';


export const calculateVelocity = (productId: string, transactions: Transaction[]) => {
    const thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);

    const productTransactions = transactions.filter((t: Transaction) =>
        new Date(t.date) >= thirtyDaysAgo &&
        t.items.some((item: any) => item.productId === productId)
    );

    const totalSold = productTransactions.reduce((sum: number, t: Transaction) => {
        const item = t.items.find((i: any) => i.productId === productId);
        return sum + (item?.quantity || 0);
    }, 0);

    return totalSold / 30;
};


export const getForecast = (product: Product, transactions: Transaction[], upcomingRain: boolean) => {
    const total = (Number(product.quantity) + Number(product.newStockQuantity || 0));
    const velocity = calculateVelocity(product.id, transactions);
    const daysRemaining = velocity > 0 
        ? Math.floor(total / velocity) 
        : (total === 0 ? 0 : Infinity);

    // Base monthly need
    let monthlyNeeded = Math.ceil(velocity * 30);

    const reorderRecommendation = Math.max(0, monthlyNeeded - total);

    // Fallback: If stock is below reorder level but reorderRecommendation is 0 (low velocity)
    // recommend ordering at least up to reorder level + some buffer
    let finalRecommendation = reorderRecommendation;
    if (total < product.reorderLevel && finalRecommendation === 0) {
        finalRecommendation = Math.max(product.reorderLevel * 2 - total, 10);
    }

    // Calculate Buy Date (Stockout Date - 2 days for lead time)
    const buyDate = new Date();
    if (daysRemaining !== Infinity && daysRemaining > 0) {
        // Recommend buying 7 days BEFORE stockout (1 week lead time)
        buyDate.setDate(buyDate.getDate() + daysRemaining - 7); 
    }

    return {
        velocity: velocity.toFixed(2),
        daysRemaining,
        reorderRecommendation: finalRecommendation,
        isHighDemand: velocity > 1,
        recommendedBuyDate: daysRemaining === Infinity ? 'N/A' : buyDate.toLocaleDateString(),
        stockOutDate: daysRemaining === Infinity ? 'N/A' : new Date(Date.now() + daysRemaining * 86400000).toLocaleDateString()
    };
};
