import '../core/config/app_config.dart';

class ServiceCountryModel {
  final int id;
  final int serviceId;
  final int countryId;
  final double price;
  final int? pricePoints;
  final double? coefficient;
  final bool active;
  final bool isPublished;
  final bool isAuto;
  final String? serviceName;
  final String? serviceNameEn;
  final String? serviceIcon;
  final String? countryName;
  final String? countryNameEn;
  final String? countryCode;
  final String? countryFlag;
  final String? countryPhoneCode;
  final String? countryFlagEmoji;

  ServiceCountryModel({
    required this.id,
    required this.serviceId,
    required this.countryId,
    required this.price,
    this.pricePoints,
    this.coefficient,
    required this.active,
    required this.isPublished,
    required this.isAuto,
    this.serviceName,
    this.serviceNameEn,
    this.serviceIcon,
    this.countryName,
    this.countryNameEn,
    this.countryCode,
    this.countryFlag,
    this.countryPhoneCode,
    this.countryFlagEmoji,
  });

  factory ServiceCountryModel.fromJson(Map<String, dynamic> json) {
    return ServiceCountryModel(
      id: json['id'] ?? 0,
      serviceId: json['service_id'] ?? 0,
      countryId: json['country_id'] ?? 0,
      price: double.tryParse(json['price']?.toString() ?? '0') ?? 0.0,
      pricePoints: json['price_points'] != null ? int.tryParse(json['price_points'].toString()) : null,
      coefficient: json['coefficient'] != null ? double.tryParse(json['coefficient'].toString()) : null,
      active: json['active'] == 1 || json['active'] == true,
      isPublished: json['is_published'] == 1 || json['is_published'] == true,
      isAuto: json['is_auto'] == 1 || json['is_auto'] == true,
      serviceName: json['service_name'],
      serviceNameEn: json['service_name_en'],
      serviceIcon: json['service_icon'],
      countryName: json['name'] ?? json['country_name'],
      countryNameEn: json['name_en'] ?? json['country_name_en'],
      countryCode: json['code'] ?? json['country_code'],
      countryFlag: json['flag'] ?? json['country_flag'],
      countryPhoneCode: json['phone_code'] ?? json['country_phone_code'],
      countryFlagEmoji: json['country_flag_emoji'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'service_id': serviceId,
      'country_id': countryId,
      'price': price,
      'active': active,
      'is_published': isPublished,
      'is_auto': isAuto,
      'service_name': serviceName,
      'service_name_en': serviceNameEn,
      'service_icon': serviceIcon,
      'name': countryName,
      'name_en': countryNameEn,
      'country_name': countryName,
      'country_name_en': countryNameEn,
      'code': countryCode,
      'flag': countryFlag,
      'country_flag': countryFlag,
      'phone_code': countryPhoneCode,
      'country_phone_code': countryPhoneCode,
      'country_flag_emoji': countryFlagEmoji,
    };
  }

  String get serviceDisplayName => serviceNameEn ?? serviceName ?? '';

  String get countryDisplayName {
    if (countryNameEn != null && countryName != null && countryNameEn!.isNotEmpty && countryName!.isNotEmpty) {
      return '$countryNameEn ($countryName)';
    }
    return countryNameEn ?? countryName ?? '';
  }

  String get localCountryFlag {
    if (countryFlag == null || countryFlag!.isEmpty) return '';
    return countryFlag!;
  }

  String get localServiceIcon {
    if (serviceIcon == null || serviceIcon!.isEmpty) return '';
    return serviceIcon!;
  }
}
