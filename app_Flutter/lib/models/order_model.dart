import '../core/i18n/app_localizations.dart';
import '../core/config/app_config.dart';

class OrderModel {
  final String id;
  final String userId;
  final int serviceId;
  final int countryId;
  final int pricePoints;
  final String status;
  final String? heroOrderId;
  final String? phoneNumber;
  final String? smsCode;
  final String? expiresAt;
  final String? activatedAt;
  final String? completedAt;
  final String createdAt;
  final String updatedAt;
  final String? serviceName;
  final String? serviceNameEn;
  final String? serviceIcon;
  final String? serviceCode;
  final String? countryName;
  final String? countryNameEn;
  final String? countryCode;
  final String? countryFlag;
  final String? heroCountryId;
  final String? batchId;

  OrderModel({
    required this.id,
    required this.userId,
    required this.serviceId,
    required this.countryId,
    required this.pricePoints,
    required this.status,
    this.heroOrderId,
    this.phoneNumber,
    this.smsCode,
    this.expiresAt,
    this.activatedAt,
    this.completedAt,
    this.createdAt = '',
    this.updatedAt = '',
    this.serviceName,
    this.serviceNameEn,
    this.serviceIcon,
    this.serviceCode,
    this.countryName,
    this.countryNameEn,
    this.countryCode,
    this.countryFlag,
    this.heroCountryId,
    this.batchId,
  });

  /// 不可变更新：仅修改传入的非空字段，避免 30+ 行的手动重建
  OrderModel copyWith({
    String? id,
    String? userId,
    int? serviceId,
    int? countryId,
    int? pricePoints,
    String? status,
    String? heroOrderId,
    String? phoneNumber,
    String? smsCode,
    String? expiresAt,
    String? activatedAt,
    String? completedAt,
    String? createdAt,
    String? updatedAt,
    String? serviceName,
    String? serviceNameEn,
    String? serviceIcon,
    String? serviceCode,
    String? countryName,
    String? countryNameEn,
    String? countryCode,
    String? countryFlag,
    String? heroCountryId,
    String? batchId,
  }) {
    return OrderModel(
      id: id ?? this.id,
      userId: userId ?? this.userId,
      serviceId: serviceId ?? this.serviceId,
      countryId: countryId ?? this.countryId,
      pricePoints: pricePoints ?? this.pricePoints,
      status: status ?? this.status,
      heroOrderId: heroOrderId ?? this.heroOrderId,
      phoneNumber: phoneNumber ?? this.phoneNumber,
      smsCode: smsCode ?? this.smsCode,
      expiresAt: expiresAt ?? this.expiresAt,
      activatedAt: activatedAt ?? this.activatedAt,
      completedAt: completedAt ?? this.completedAt,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
      serviceName: serviceName ?? this.serviceName,
      serviceNameEn: serviceNameEn ?? this.serviceNameEn,
      serviceIcon: serviceIcon ?? this.serviceIcon,
      serviceCode: serviceCode ?? this.serviceCode,
      countryName: countryName ?? this.countryName,
      countryNameEn: countryNameEn ?? this.countryNameEn,
      countryCode: countryCode ?? this.countryCode,
      countryFlag: countryFlag ?? this.countryFlag,
      heroCountryId: heroCountryId ?? this.heroCountryId,
      batchId: batchId ?? this.batchId,
    );
  }

  factory OrderModel.fromJson(Map<String, dynamic> json) {
    // 解析价格：可能是 total_price (字符串) 或 price_points (整数)
    int parsePricePoints(dynamic value) {
      if (value == null) return 0;
      if (value is int) return value;
      if (value is double) return value.toInt();
      if (value is String) {
        // 尝试解析字符串，可能是 "8.00" 这样的格式
        final parsed = double.tryParse(value);
        if (parsed != null) return parsed.toInt();
      }
      return 0;
    }

    return OrderModel(
      id: json['id']?.toString() ?? '',
      userId: json['user_id']?.toString() ?? '',
      serviceId: json['service_id'] is int ? json['service_id'] : int.tryParse(json['service_id']?.toString() ?? '0') ?? 0,
      countryId: json['country_id'] is int ? json['country_id'] : int.tryParse(json['country_id']?.toString() ?? '0') ?? 0,
      pricePoints: parsePricePoints(
        json['total_price'] ?? 
        json['price_points'] ?? 
        json['price'] ??
        json['total_cost']
      ),
      status: json['status'] ?? 'pending',
      heroOrderId: json['hero_order_id']?.toString(),
      phoneNumber: json['phone_number']?.toString(),
      smsCode: json['sms_code']?.toString(),
      expiresAt: json['expires_at']?.toString(),
      activatedAt: json['activated_at']?.toString(),
      completedAt: json['completed_at']?.toString(),
      createdAt: json['created_at']?.toString() ?? '',
      updatedAt: json['updated_at']?.toString() ?? '',
      serviceName: json['service_name']?.toString(),
      serviceNameEn: json['service_name_en']?.toString(),
      serviceIcon: json['service_icon']?.toString(),
      serviceCode: json['service_code']?.toString(),
      countryName: json['country_name']?.toString(),
      countryNameEn: json['country_name_en']?.toString(),
      countryCode: json['country_code']?.toString(),
      countryFlag: json['country_flag']?.toString(),
      heroCountryId: json['hero_country_id']?.toString(),
      batchId: json['batch_id']?.toString(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'user_id': userId,
      'service_id': serviceId,
      'country_id': countryId,
      'price_points': pricePoints,
      'status': status,
      'hero_order_id': heroOrderId,
      'phone_number': phoneNumber,
      'sms_code': smsCode,
      'expires_at': expiresAt,
      'activated_at': activatedAt,
      'completed_at': completedAt,
      'created_at': createdAt,
      'updated_at': updatedAt,
      'service_name': serviceName,
      'service_name_en': serviceNameEn,
      'service_icon': serviceIcon,
      'service_code': serviceCode,
      'country_name': countryName,
      'country_name_en': countryNameEn,
      'country_code': countryCode,
      'country_flag': countryFlag,
      'hero_country_id': heroCountryId,
    };
  }

  String get serviceDisplayName => serviceNameEn ?? serviceName ?? '';
  String get countryDisplayName => countryNameEn ?? countryName ?? '';

  String statusLabel(AppLocalizations loc) {
    switch (status) {
      case 'pending':
        return loc.translate('pending');
      case 'active':
        return loc.translate('active');
      case 'completed':
        return loc.translate('completed');
      case 'expired':
        return loc.translate('expired');
      case 'cancelled':
        return loc.translate('cancelled');
      case 'refunded':
        return loc.translate('refund');
      default:
        return status;
    }
  }

  bool get canActivate => status == 'pending';
  bool get canCancel => status == 'pending' || status == 'active';
  bool get isActive => status == 'active';
  bool get isPending => status == 'pending';
  bool get isCompleted => status == 'completed';
  bool get isExpired => status == 'expired';
}
