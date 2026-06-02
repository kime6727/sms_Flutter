class PaymentPackageModel {
  final String id;
  final String productId;
  final String name;
  final int points;
  final double price;
  final String description;
  final bool isRecommended;

  PaymentPackageModel({
    required this.id,
    required this.productId,
    required this.name,
    required this.points,
    required this.price,
    required this.description,
    required this.isRecommended,
  });

  factory PaymentPackageModel.fromJson(Map<String, dynamic> json) {
    return PaymentPackageModel(
      id: (json['id'] ?? json['product_id'] ?? '').toString(),
      productId: json['product_id'] ?? '',
      name: json['name'] ?? json['config_name'] ?? '',
      points: int.tryParse((json['points'] ?? json['credits'] ?? 0).toString()) ?? 0,
      price: double.tryParse(json['price']?.toString() ?? json['display_price']?.toString() ?? '0') ?? 0.0,
      description: json['description'] ?? '',
      isRecommended: json['is_recommended'] == 1 || json['is_recommended'] == true,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'product_id': productId,
      'name': name,
      'points': points,
      'price': price,
      'description': description,
      'is_recommended': isRecommended,
    };
  }

  String get displayName => name;
}
