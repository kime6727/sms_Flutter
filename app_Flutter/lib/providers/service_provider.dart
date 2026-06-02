import 'package:flutter/foundation.dart';
import '../models/service_model.dart';
import '../models/country_model.dart';
import '../models/service_country_model.dart';
import '../services/api_service.dart';

class ServiceProvider extends ChangeNotifier {
  final ApiService _apiService;

  List<ServiceModel> _services = [];
  List<CountryModel> _countries = [];
  List<ServiceCountryModel> _serviceCountries = [];
  bool _isLoading = false;
  String? _error;

  List<ServiceModel> get services => _services;
  List<CountryModel> get countries => _countries;
  List<ServiceCountryModel> get serviceCountries => _serviceCountries;
  bool get isLoading => _isLoading;
  String? get error => _error;

  ServiceProvider(this._apiService);

  Future<void> loadServices() async {
    _isLoading = true;
    notifyListeners();

    try {
      final response = await _apiService.getServices();
      if (response['data'] != null) {
        final list = response['data'] as List;
        _services = list.map((e) => ServiceModel.fromJson(e)).toList();
      }
    } on ApiException catch (e) {
      _error = e.message;
    } catch (e) {
      _error = 'Failed to load services';
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> loadCountries() async {
    _isLoading = true;
    notifyListeners();

    try {
      final response = await _apiService.getCountries();
      if (response['data'] != null) {
        final list = response['data'] as List;
        _countries = list.map((e) => CountryModel.fromJson(e)).toList();
      }
    } on ApiException catch (e) {
      _error = e.message;
    } catch (e) {
      _error = 'Failed to load countries';
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> loadServiceCountries({int? serviceId, int? countryId}) async {
    _isLoading = true;
    notifyListeners();

    try {
      final response = await _apiService.getServiceCountries(
        serviceId: serviceId,
        countryId: countryId,
      );
      if (response['data'] != null) {
        final list = response['data'] as List;
        _serviceCountries =
            list.map((e) => ServiceCountryModel.fromJson(e)).toList();
      }
    } on ApiException catch (e) {
      _error = e.message;
    } catch (e) {
      _error = 'Failed to load service countries';
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> loadPublishedServiceCountries({int? serviceId}) async {
    _isLoading = true;
    notifyListeners();

    try {
      final response = await _apiService.getPublishedServiceCountries(
        serviceId: serviceId,
      );
      if (response['data'] != null) {
        final list = response['data'] as List;
        _serviceCountries =
            list.map((e) => ServiceCountryModel.fromJson(e)).toList();
      }
    } on ApiException catch (e) {
      _error = e.message;
    } catch (e) {
      _error = 'Failed to load published service countries';
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<Map<String, dynamic>> calculatePrice({
    required int serviceId,
    required int countryId,
  }) async {
    return await _apiService.calculatePrice(
      serviceId: serviceId,
      countryId: countryId,
    );
  }

  ServiceCountryModel? getServiceCountry(int serviceId, int countryId) {
    try {
      return _serviceCountries.firstWhere(
        (sc) => sc.serviceId == serviceId && sc.countryId == countryId,
      );
    } catch (e) {
      return null;
    }
  }

  List<ServiceCountryModel> getCountriesForService(int serviceId) {
    return _serviceCountries
        .where((sc) => sc.serviceId == serviceId)
        .toList();
  }

  List<ServiceCountryModel> getServicesForCountry(int countryId) {
    return _serviceCountries
        .where((sc) => sc.countryId == countryId)
        .toList();
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }
}
