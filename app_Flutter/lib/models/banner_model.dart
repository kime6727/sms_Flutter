class BannerModel {
  final int id;
  final String name;
  final String imageUrl;
  final String linkUrl;
  final int sortOrder;

  BannerModel({
    required this.id,
    required this.name,
    required this.imageUrl,
    required this.linkUrl,
    required this.sortOrder,
  });

  factory BannerModel.fromJson(Map<String, dynamic> json) {
    return BannerModel(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      name: json['name']?.toString() ?? '',
      imageUrl: json['image_url']?.toString() ?? '',
      linkUrl: json['link_url']?.toString() ?? '',
      sortOrder: json['sort_order'] is int ? json['sort_order'] : int.tryParse(json['sort_order']?.toString() ?? '0') ?? 0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'image_url': imageUrl,
      'link_url': linkUrl,
      'sort_order': sortOrder,
    };
  }
}
