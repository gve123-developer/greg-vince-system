import { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/app/components/ui/card';
import { Button } from '@/app/components/ui/button';
import { Input } from '@/app/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/app/components/ui/table';
import { Badge } from '@/app/components/ui/badge';
import { Product, Transaction } from '@/app/App';
import { ErrorBoundary } from '@/app/components/ErrorBoundary';
import {
    TrendingUp,
    TrendingDown,
    AlertTriangle,
    Info,
    Package,
    ArrowRight,
    ShieldCheck,
    Search,
    ChevronLeft,
    ChevronRight,
    RefreshCw
} from 'lucide-react';

interface StockForecastingProps {
    products: Product[];
    transactions: Transaction[];
}

import { getForecast, calculateAccuracyMetrics } from '@/app/utils/forecastingUtils';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip as RechartsTooltip, Legend, ResponsiveContainer } from 'recharts';

export function StockForecasting({ products, transactions }: StockForecastingProps) {
    const [searchTerm, setSearchTerm] = useState('');
    const [currentPage, setCurrentPage] = useState(1);
    const itemsPerPage = 8;
    
    // Calculation: (Stock / Velocity) = Days left
    // ... rest of the code ...

    const [showMetrics, setShowMetrics] = useState(false);
    const [accuracyTimeframe, setAccuracyTimeframe] = useState<number>(30);
    const [accuracyMetrics, setAccuracyMetrics] = useState<{sma: any, exponentialSmoothing: any, chartData?: any[]} | null>(null);

    useEffect(() => {
        setCurrentPage(1);
    }, [searchTerm]);

    useEffect(() => {
        let totalSmaMape = 0, totalSmaMae = 0, totalSmaRmse = 0;
        let totalEsMape = 0, totalEsMae = 0, totalEsRmse = 0;
        let validProductsCount = 0;
        
        let aggregatedChartData: any[] = [];

        products.forEach(p => {
            const metrics = calculateAccuracyMetrics(p.id, transactions, accuracyTimeframe);
            if (metrics) {
                totalSmaMape += parseFloat(metrics.sma.mape);
                totalSmaMae += parseFloat(metrics.sma.mae);
                totalSmaRmse += parseFloat(metrics.sma.rmse);

                totalEsMape += parseFloat(metrics.exponentialSmoothing.mape);
                totalEsMae += parseFloat(metrics.exponentialSmoothing.mae);
                totalEsRmse += parseFloat(metrics.exponentialSmoothing.rmse);
                validProductsCount++;
                
                if (aggregatedChartData.length === 0) {
                    aggregatedChartData = metrics.chartData.map(d => ({ ...d }));
                } else {
                    metrics.chartData.forEach((dayData, idx) => {
                        if (aggregatedChartData[idx]) {
                            aggregatedChartData[idx].Actual += dayData.Actual;
                            aggregatedChartData[idx].SMA += dayData.SMA;
                            aggregatedChartData[idx]['Exp. Smoothing'] += dayData['Exp. Smoothing'];
                        }
                    });
                }
            }
        });

        if (validProductsCount > 0) {
            setAccuracyMetrics({
                sma: {
                    mape: (totalSmaMape / validProductsCount).toFixed(2) + '%',
                    mae: (totalSmaMae / validProductsCount).toFixed(2),
                    rmse: (totalSmaRmse / validProductsCount).toFixed(2)
                },
                exponentialSmoothing: {
                    mape: (totalEsMape / validProductsCount).toFixed(2) + '%',
                    mae: (totalEsMae / validProductsCount).toFixed(2),
                    rmse: (totalEsRmse / validProductsCount).toFixed(2)
                },
                chartData: aggregatedChartData
            });
        }
    }, [products, transactions, accuracyTimeframe]);

    const getProductForecast = (product: Product) => {
        return getForecast(product, transactions, false);
    };


    const filteredProducts = products
        .filter(p =>
            p.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            p.sku.toLowerCase().includes(searchTerm.toLowerCase())
        )
        .sort((a, b) => {
            const recA = getProductForecast(a).reorderRecommendation;
            const recB = getProductForecast(b).reorderRecommendation;
            return recB - recA; // Push recommendations to the top
        });

    const totalPages = Math.max(1, Math.ceil(filteredProducts.length / itemsPerPage));
    const paginatedProducts = filteredProducts.slice(
        (currentPage - 1) * itemsPerPage,
        currentPage * itemsPerPage
    );

    const stats = {
        criticalStock: products.filter(p => {
            const { daysRemaining } = getProductForecast(p);
            return daysRemaining <= 7;
        }).length,
        highVelocity: products.filter(p => {
            const { velocity } = getProductForecast(p);
            return parseFloat(velocity) > 2;
        }).length,
        totalRecommendations: products.filter(p => getProductForecast(p).reorderRecommendation > 0).length
    };

    return (
        <ErrorBoundary fallbackTitle="Stock Forecasting Module Error">
            <div className="space-y-6">
                <div className="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <div>
                        <h2 className="text-2xl font-semibold text-gray-900">Stock Forecasting</h2>
                        <p className="text-sm text-gray-500 mt-1">Predictive insights based on sales velocity and environmental data.</p>
                    </div>
                    <Button onClick={() => setShowMetrics(!showMetrics)} variant="outline" className="flex items-center gap-2">
                        <TrendingUp className="size-4" />
                        {showMetrics ? "Hide Accuracy Metrics" : "View Accuracy Metrics (SMA vs ES)"}
                    </Button>
                </div>

                {showMetrics && accuracyMetrics && (
                    <Card className="bg-white border-2 border-indigo-200 shadow-md mt-6">
                        <CardHeader className="bg-indigo-50 border-b border-indigo-100 pb-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div>
                                <CardTitle className="text-indigo-900 flex items-center gap-2">
                                    <ShieldCheck className="size-5 text-indigo-600" />
                                    Algorithmic Accuracy Report (Objective #3)
                                </CardTitle>
                                <CardDescription className="text-indigo-700 mt-1">
                                    Historical backtesting comparing Simple Moving Average (SMA) baseline vs. Custom Algorithmic Forecasting (Exponential Smoothing, α=0.7).
                                </CardDescription>
                            </div>
                            <div className="flex bg-white p-1 rounded-lg border border-indigo-200 shadow-sm">
                                <button 
                                    onClick={() => setAccuracyTimeframe(7)}
                                    className={`px-3 py-1 text-xs font-bold rounded-md transition-colors ${accuracyTimeframe === 7 ? 'bg-indigo-600 text-white' : 'text-indigo-600 hover:bg-indigo-50'}`}
                                >
                                    1 Week
                                </button>
                                <button 
                                    onClick={() => setAccuracyTimeframe(30)}
                                    className={`px-3 py-1 text-xs font-bold rounded-md transition-colors ${accuracyTimeframe === 30 ? 'bg-indigo-600 text-white' : 'text-indigo-600 hover:bg-indigo-50'}`}
                                >
                                    30 Days
                                </button>
                            </div>
                        </CardHeader>
                        <CardContent className="pt-6 space-y-6">
                            {accuracyMetrics.chartData && accuracyMetrics.chartData.length > 0 && (
                                <div className="h-64 w-full border border-gray-100 rounded-lg p-4 bg-gray-50/50">
                                    <h3 className="text-xs font-bold text-gray-500 uppercase tracking-widest text-center mb-4">Actual vs Predicted Demand ({accuracyTimeframe} Days)</h3>
                                    <ResponsiveContainer width="100%" height="100%">
                                        <LineChart data={accuracyMetrics.chartData} margin={{ top: 5, right: 20, left: -20, bottom: 5 }}>
                                            <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e5e7eb" />
                                            <XAxis dataKey="date" tick={{fontSize: 10, fill: '#6b7280'}} tickLine={false} axisLine={false} />
                                            <YAxis tick={{fontSize: 10, fill: '#6b7280'}} tickLine={false} axisLine={false} />
                                            <RechartsTooltip 
                                                contentStyle={{ borderRadius: '8px', border: 'none', boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)' }}
                                                itemStyle={{ fontSize: '12px', fontWeight: 'bold' }}
                                                labelStyle={{ fontSize: '10px', color: '#6b7280', textTransform: 'uppercase', fontWeight: 'bold' }}
                                            />
                                            <Legend wrapperStyle={{ fontSize: '11px', paddingTop: '10px' }} />
                                            <Line type="monotone" dataKey="Actual" stroke="#111827" strokeWidth={3} dot={false} activeDot={{ r: 4 }} />
                                            <Line type="monotone" dataKey="SMA" stroke="#9ca3af" strokeWidth={2} strokeDasharray="5 5" dot={false} />
                                            <Line type="monotone" dataKey="Exp. Smoothing" stroke="#4f46e5" strokeWidth={3} dot={false} activeDot={{ r: 4 }} />
                                        </LineChart>
                                    </ResponsiveContainer>
                                </div>
                            )}
                            
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div className="space-y-4 border rounded-lg p-4 bg-gray-50">
                                    <h3 className="font-bold text-gray-700 text-center uppercase tracking-wider text-sm">Baseline: SMA</h3>
                                    <div className="flex justify-between items-center border-b pb-2">
                                        <span className="text-gray-500 font-medium">MAPE (Error %)</span>
                                        <span className="font-bold text-gray-900 text-lg">{accuracyMetrics.sma.mape}</span>
                                    </div>
                                    <div className="flex justify-between items-center border-b pb-2">
                                        <span className="text-gray-500 font-medium">MAE (Absolute Error)</span>
                                        <span className="font-bold text-gray-900">{accuracyMetrics.sma.mae} units</span>
                                    </div>
                                    <div className="flex justify-between items-center">
                                        <span className="text-gray-500 font-medium">RMSE (Squared Error)</span>
                                        <span className="font-bold text-gray-900">{accuracyMetrics.sma.rmse}</span>
                                    </div>
                                </div>
                                <div className="space-y-4 border-2 border-indigo-200 rounded-lg p-4 bg-indigo-50/50 relative overflow-hidden">
                                    <div className="absolute -right-6 -top-6 bg-green-500 text-white text-[9px] font-black px-8 py-1 rotate-45 transform origin-bottom-left uppercase tracking-widest shadow-sm">Winner</div>
                                    <h3 className="font-bold text-indigo-900 text-center uppercase tracking-wider text-sm">Custom Algorithmic Forecasting (Exp. Smoothing)</h3>
                                    <div className="flex justify-between items-center border-b border-indigo-100 pb-2">
                                        <span className="text-indigo-700 font-medium flex items-center gap-2">MAPE (Error %) <Badge className="bg-green-500 hover:bg-green-600 text-white text-[9px] px-1 py-0 leading-none">LOWER IS BETTER</Badge></span>
                                        <span className="font-black text-indigo-900 text-xl">{accuracyMetrics.exponentialSmoothing.mape}</span>
                                    </div>
                                    <div className="flex justify-between items-center border-b border-indigo-100 pb-2">
                                        <span className="text-indigo-700 font-medium">MAE (Absolute Error)</span>
                                        <span className="font-bold text-indigo-900">{accuracyMetrics.exponentialSmoothing.mae} units</span>
                                    </div>
                                    <div className="flex justify-between items-center">
                                        <span className="text-indigo-700 font-medium">RMSE (Squared Error)</span>
                                        <span className="font-bold text-indigo-900">{accuracyMetrics.exponentialSmoothing.rmse}</span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

            <ErrorBoundary fallbackTitle="Forecasting Summary Error">
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <Card className="bg-green-50 border-2 border-green-200 shadow-lg transition-all duration-300 hover:scale-[1.02] hover:-translate-y-1 hover:shadow-xl">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-bold text-green-900 uppercase tracking-wider">Inventory Health</CardTitle>
                            <ShieldCheck className="size-6 text-green-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-3xl font-black text-green-700">{products.length - stats.criticalStock}</div>
                            <p className="text-xs font-semibold text-green-600 mt-1 uppercase tracking-wider">Items in Safe Zone</p>
                        </CardContent>
                    </Card>

                    <Card className="bg-amber-50 border-2 border-amber-200 shadow-lg transition-all duration-300 hover:scale-[1.02] hover:-translate-y-1 hover:shadow-xl font-medium">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-bold text-amber-900 uppercase tracking-wider">Critical Risk</CardTitle>
                            <AlertTriangle className="size-6 text-amber-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-3xl font-black text-amber-700">{stats.criticalStock}</div>
                            <p className="text-xs font-semibold text-amber-600 mt-1 uppercase tracking-wider">Runs out &lt; 7 Days</p>
                        </CardContent>
                    </Card>

                    <Card className="bg-blue-50 border-2 border-blue-200 shadow-lg transition-all duration-300 hover:scale-[1.02] hover:-translate-y-1 hover:shadow-xl font-medium">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-bold text-blue-900 uppercase tracking-wider">Daily Demand</CardTitle>
                            <TrendingUp className="size-6 text-blue-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-3xl font-black text-blue-700">{stats.highVelocity}</div>
                            <p className="text-xs font-semibold text-blue-600 mt-1 uppercase tracking-wider">Fast Moving Items</p>
                        </CardContent>
                    </Card>
                </div>
            </ErrorBoundary>

            <ErrorBoundary fallbackTitle="Forecast Insights Error">
                <Card className="border border-gray-200 overflow-hidden shadow-sm">
                    <CardHeader className="bg-gray-50 border-b border-gray-200 flex flex-row items-center justify-between space-y-0 p-6">
                        <div>
                            <CardTitle className="text-lg font-bold text-gray-900 uppercase tracking-tight">Forecast Insights</CardTitle>
                            <CardDescription className="text-sm text-gray-500">Estimated stock duration and dynamic reorder recommendations (14-days for fast-moving, 30-days for slow-moving) based on Exponential Smoothing.</CardDescription>
                        </div>
                        <div className="max-w-xs relative">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-gray-400" />
                            <Input
                                placeholder="Search products..."
                                value={searchTerm}
                                onChange={(e: React.ChangeEvent<HTMLInputElement>) => setSearchTerm(e.target.value)}
                                className="h-10 pl-10 bg-white border-gray-200"
                            />
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto w-full">
                            <Table className="w-full min-w-[800px]">
                            <TableHeader>
                                <TableRow className="bg-gray-100/50 border-b border-gray-200">
                                    <TableHead className="px-2 py-3 font-black text-gray-700 uppercase text-[9px] w-[20%]">Product Name</TableHead>
                                    <TableHead className="px-1 py-3 font-black text-gray-700 uppercase text-[9px] text-center">Daily Velocity</TableHead>
                                    <TableHead className="px-1 py-3 font-black text-gray-700 uppercase text-[9px] text-center">Current Stock</TableHead>
                                    <TableHead className="px-1 py-3 font-black text-gray-700 uppercase text-[9px] text-center">Stock Status</TableHead>
                                    <TableHead className="px-1 py-3 font-black text-gray-700 uppercase text-[9px] text-center text-red-600">Days Left</TableHead>
                                    <TableHead className="px-1 py-3 font-black text-gray-700 uppercase text-[9px] text-center">Stockout Date</TableHead>
                                    <TableHead className="px-1 py-3 font-black text-gray-700 uppercase text-[9px] text-center">Reorder Date</TableHead>
                                    <TableHead className="px-2 py-3 font-black text-gray-700 uppercase text-[9px] text-right">Recommendation</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody className="divide-y divide-gray-200">
                                {paginatedProducts.map((p) => {
                                    const forecast = getProductForecast(p);
                                    
                                    return (
                                        <TableRow key={p.id} className="hover:bg-gray-50/50 transition-colors">
                                            <TableCell className="px-3 py-4 border-r border-gray-200">
                                                <div className="flex flex-col">
                                                    <span className="font-bold text-gray-900 text-sm">{p.name}</span>
                                                    <span className="text-[10px] text-gray-400 font-mono uppercase tracking-tighter mt-0.5">{p.sku}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="px-6 py-4 border-r border-gray-200 text-center">
                                                <div className="flex items-center justify-center gap-1.5">
                                                    <span className="font-mono font-bold text-sm text-gray-700">{forecast.velocity}</span>
                                                    {parseFloat(forecast.velocity) > 0 ? (
                                                        <TrendingUp className="size-3 text-green-600" />
                                                    ) : (
                                                        <TrendingDown className="size-3 text-gray-400" />
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell className="px-6 py-4 border-r border-gray-200 text-center">
                                                <div className="flex flex-col items-center justify-center">
                                                    <span className={`text-sm font-black ${(Number(p.quantity) + Number(p.newStockQuantity || 0)) === 0 ? 'text-red-600' : 'text-blue-800'}`}>
                                                        {Number(p.quantity) + Number(p.newStockQuantity || 0)}
                                                    </span>
                                                    <span className="text-[9px] text-gray-400 font-bold uppercase">Total Units</span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="px-6 py-4 border-r border-gray-200 text-center">
                                                {Number((forecast as any).daysRemaining) <= 7 || (Number(p.quantity) + Number(p.newStockQuantity || 0)) === 0 ? (
                                                    <Badge className="bg-red-100 text-red-800 border-none px-2 py-0.5 text-[10px] font-black uppercase tracking-widest leading-none">CRITICAL</Badge>
                                                ) : (Number((forecast as any).daysRemaining) <= 14 || (Number(p.quantity) + Number(p.newStockQuantity || 0)) <= Number(p.reorderLevel)) ? (
                                                    <Badge className="bg-orange-100 text-orange-800 border-none px-2 py-0.5 text-[10px] font-bold uppercase tracking-widest leading-none">LOW STOCK</Badge>
                                                ) : (
                                                    <Badge className="bg-green-100 text-green-800 border-none px-2 py-0.5 text-[10px] font-bold uppercase tracking-widest leading-none">OPTIMAL</Badge>
                                                )}
                                            </TableCell>
                                            <TableCell className="px-6 py-4 border-r border-gray-200 text-center">
                                                <span className={`font-black text-sm ${(forecast as any).daysRemaining <= 7 || (Number(p.quantity) + Number(p.newStockQuantity || 0)) === 0 ? 'text-red-600' :
                                                    ((forecast as any).daysRemaining <= 14 || (Number(p.quantity) + Number(p.newStockQuantity || 0)) <= p.reorderLevel) ? 'text-orange-600' : 'text-green-600'
                                                    }`}>
                                                    {(Number(p.quantity) + Number(p.newStockQuantity || 0)) === 0 ? '0' : ((forecast as any).daysRemaining === Infinity ? 'STABLE' : (forecast as any).daysRemaining)}
                                                </span>
                                            </TableCell>
                                            <TableCell className="px-6 py-4 border-r border-gray-200 text-center">
                                                <span className="text-xs font-mono font-bold text-gray-600 whitespace-nowrap uppercase">
                                                    {(forecast as any).stockOutDate !== 'N/A' ? new Date((forecast as any).stockOutDate).toLocaleDateString([], { year: 'numeric', month: 'short', day: 'numeric' }) : 'SUFFICIENT'}
                                                </span>
                                            </TableCell>
                                            <TableCell className="px-6 py-4 border-r border-gray-200 text-center">
                                                <div className="flex flex-col">
                                                    <span className={`text-xs font-black ${((forecast as any).daysRemaining <= 5) ? 'text-red-600' : 'text-blue-700'} whitespace-nowrap`}>
                                                        {(forecast as any).recommendedBuyDate !== 'N/A' ? new Date((forecast as any).recommendedBuyDate).toLocaleDateString([], { year: 'numeric', month: 'short', day: 'numeric' }) : '-'}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="px-3 py-4 text-right">
                                                {forecast.reorderRecommendation > 0 ? (
                                                    <div className="inline-flex items-center gap-1.5 text-blue-700 bg-blue-50 border border-blue-100 px-2 py-0.5 rounded-full font-black text-[11px]">
                                                        +{forecast.reorderRecommendation}
                                                        <ArrowRight className="size-3" />
                                                    </div>
                                                ) : (
                                                    <span className="text-[10px] font-bold text-gray-400 uppercase tracking-widest leading-none">NONE</span>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                        </div>
                    </CardContent>
                    {/* Pagination Controls */}
                    <div className="bg-gray-50 border-t border-gray-200 px-6 py-4 flex items-center justify-between">
                        <div className="text-sm text-gray-500 font-medium">
                            Showing <span className="text-gray-900 font-bold">{Math.min(filteredProducts.length, (currentPage - 1) * itemsPerPage + 1)}</span> to <span className="text-gray-900 font-bold">{Math.min(filteredProducts.length, currentPage * itemsPerPage)}</span> of <span className="text-gray-900 font-bold">{filteredProducts.length}</span> products
                        </div>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setCurrentPage(p => Math.max(1, p - 1))}
                                disabled={currentPage === 1}
                                className="bg-white border-gray-200 hover:bg-gray-100 disabled:opacity-50"
                            >
                                <ChevronLeft className="size-4 mr-1" />
                                Previous
                            </Button>
                            <div className="flex items-center gap-1 hidden sm:flex">
                                {(() => {
                                    const pages = [];
                                    let start = Math.max(1, currentPage - 1);
                                    if (start + 2 > totalPages) start = Math.max(1, totalPages - 2);
                                    let end = Math.min(totalPages, start + 2);

                                    for (let i = start; i <= end; i++) {
                                        pages.push(i);
                                    }

                                    return (
                                        <>
                                            {start > 1 && <span className="text-gray-400 px-1">...</span>}
                                            {pages.map(page => (
                                                <Button
                                                    key={page}
                                                    variant={currentPage === page ? "default" : "outline"}
                                                    size="sm"
                                                    onClick={() => setCurrentPage(page)}
                                                    className={`size-8 p-0 font-bold ${currentPage === page ? "bg-gray-900 text-white" : "bg-white border-gray-200"}`}
                                                >
                                                    {page}
                                                </Button>
                                            ))}
                                            {end < totalPages && <span className="text-gray-400 px-1">...</span>}
                                        </>
                                    );
                                })()}
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setCurrentPage(p => Math.min(totalPages, p + 1))}
                                disabled={currentPage === totalPages}
                                className="bg-white border-gray-200 hover:bg-gray-100 disabled:opacity-50"
                            >
                                Next
                                <ChevronRight className="size-4 ml-1" />
                            </Button>
                        </div>
                    </div>
                </Card>
            </ErrorBoundary>
            </div>
        </ErrorBoundary>
    );
}
