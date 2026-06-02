import 'package:flutter/foundation.dart';
import '../models/banner_model.dart';
import '../services/api_service.dart';

class BannerProvider with ChangeNotifier {
  final ApiService _apiService;
  
  List<BannerModel> _banners = [];
  bool _isLoading = false;
  String? _error;

  List<BannerModel> get banners => _banners;
  bool get isLoading => _isLoading;
  String? get error => _error;
  bool get hasBanners => _banners.isNotEmpty;

  BannerProvider(this._apiService);

  Future<void> fetchBanners() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _apiService.getBanners();
      
      if (response['success'] == true) {
        final List<dynamic> data = response['data'] ?? [];
        _banners = data.map((json) => BannerModel.fromJson(json)).toList();
        _error = null;
      } else {
        _error = response['error'] ?? '获取Banner失败';
        _banners = [];
      }
    } catch (e) {
      debugPrint('获取Banner失败: $e');
      _error = '网络错误，请稍后重试';
      _banners = [];
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }
}
