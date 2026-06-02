import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme/app_theme.dart';
import '../../core/i18n/app_localizations.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';

class TransactionHistoryScreen extends StatefulWidget {
  const TransactionHistoryScreen({super.key});

  @override
  State<TransactionHistoryScreen> createState() =>
      _TransactionHistoryScreenState();
}

class _TransactionHistoryScreenState extends State<TransactionHistoryScreen> {
  List<Map<String, dynamic>> _transactions = [];
  bool _isLoading = true;
  String? _error;
  int _currentPage = 1;
  bool _hasMore = true;

  @override
  void initState() {
    super.initState();
    _loadTransactions();
  }

  Future<void> _loadTransactions({bool refresh = false}) async {
    if (refresh) {
      _currentPage = 1;
      _hasMore = true;
      _transactions = [];
    }

    if (!_hasMore) return;

    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final apiService = context.read<ApiService>();
      final response = await apiService.getTransactions(
        page: _currentPage,
        limit: 20,
      );

      if (response['data'] != null) {
        final data = response['data'];
        List<dynamic> list;
        if (data is List) {
          list = data;
        } else if (data is Map && data['list'] != null) {
          list = data['list'] as List;
        } else {
          list = [];
        }

        final newTransactions = list.map((e) => Map<String, dynamic>.from(e as Map)).toList();
        setState(() {
          if (refresh) {
            _transactions = newTransactions;
          } else {
            _transactions.addAll(newTransactions);
          }
          _hasMore = newTransactions.length >= 20;
          _currentPage++;
        });
      }
    } catch (e) {
      setState(() {
        _error = e.toString();
      });
    } finally {
      setState(() {
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final loc = context.loc;

    return Scaffold(
      appBar: AppBar(
        title: Text(loc.translate('transaction_history')),
      ),
      body: _isLoading && _transactions.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : _error != null && _transactions.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.error_outline,
                          size: 64, color: Colors.grey[400]),
                      const SizedBox(height: 16),
                      Text(
                        loc.translate('load_failed'),
                        style: TextStyle(
                            fontSize: 16, color: Colors.grey[600]),
                      ),
                      const SizedBox(height: 16),
                      ElevatedButton(
                        onPressed: () => _loadTransactions(refresh: true),
                        child: Text(loc.translate('retry')),
                      ),
                    ],
                  ),
                )
              : _transactions.isEmpty
                  ? Center(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(Icons.receipt_long_outlined,
                              size: 64, color: Colors.grey[400]),
                          const SizedBox(height: 16),
                          Text(
                            loc.translate('no_transactions'),
                            style: TextStyle(
                                fontSize: 16, color: Colors.grey[600]),
                          ),
                        ],
                      ),
                    )
                  : RefreshIndicator(
                      onRefresh: () =>
                          _loadTransactions(refresh: true),
                      child: ListView.builder(
                        padding: const EdgeInsets.all(16),
                        itemCount:
                            _transactions.length + (_hasMore ? 1 : 0),
                        itemBuilder: (context, index) {
                          if (index == _transactions.length) {
                            if (!_isLoading) {
                              _loadTransactions();
                            }
                            return const Padding(
                              padding: EdgeInsets.all(16),
                              child: Center(
                                  child: CircularProgressIndicator()),
                            );
                          }
                          return _buildTransactionCard(
                              context, _transactions[index]);
                        },
                      ),
                    ),
    );
  }

  Widget _buildTransactionCard(
      BuildContext context, Map<String, dynamic> transaction) {
    final loc = context.loc;
    final type = transaction['type'] ?? '';
    final amount = transaction['amount'] ?? 0;
    final description = transaction['description'] ?? '';
    final createdAt = transaction['created_at'] ?? '';

    final bool isCredit = type == 'topup' || type == 'bonus' || type == 'refund';
    final bool isDebit = type == 'purchase' || type == 'consume';

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: (isCredit ? AppColors.success : AppColors.error)
                    .withOpacity(0.1),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(
                isCredit ? Icons.add_circle_outline : Icons.remove_circle_outline,
                color: isCredit ? AppColors.success : AppColors.error,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    _getTransactionTypeLabel(loc, type),
                    style: const TextStyle(
                        fontWeight: FontWeight.w600, fontSize: 15),
                  ),
                  if (description.isNotEmpty) ...[
                    const SizedBox(height: 4),
                    Text(
                      description,
                      style: TextStyle(
                          fontSize: 13, color: Colors.grey[600]),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                  const SizedBox(height: 4),
                  Text(
                    _formatDate(createdAt),
                    style: TextStyle(fontSize: 12, color: Colors.grey[500]),
                  ),
                ],
              ),
            ),
            Text(
              '${isCredit ? "+" : ""}$amount',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.bold,
                color: isCredit ? AppColors.success : AppColors.error,
              ),
            ),
          ],
        ),
      ),
    );
  }

  String _getTransactionTypeLabel(AppLocalizations loc, String type) {
    switch (type) {
      case 'topup':
        return loc.translate('topup');
      case 'purchase':
      case 'consume':
        return loc.translate('purchase');
      case 'bonus':
        return loc.translate('bonus');
      case 'refund':
        return loc.translate('refund');
      default:
        return type;
    }
  }

  String _formatDate(String dateStr) {
    if (dateStr.isEmpty) return '';
    try {
      final date = DateTime.parse(dateStr);
      return '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')} ${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}';
    } catch (e) {
      return dateStr;
    }
  }
}
