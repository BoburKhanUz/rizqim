// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data48.g.dart';

@JsonSerializable()
class Data48 {
  const Data48({
    required this.id,
    required this.name,
    required this.logo,
    required this.logoUrl,
    required this.banner,
    required this.bannerUrl,
    required this.description,
    required this.phone,
    required this.address,
    required this.locationUrl,
    required this.isOpen,
    required this.minOrderAmount,
    required this.paymentSettings,
    required this.paymentAccountType,
    required this.status,
    required this.isOrderLocked,
    required this.allowedMethods,
  });

  factory Data48.fromJson(Map<String, Object?> json) => _$Data48FromJson(json);

  final int id;
  final String name;
  final String? logo;
  @JsonKey(name: 'logo_url')
  final String? logoUrl;
  final String? banner;
  @JsonKey(name: 'banner_url')
  final String? bannerUrl;
  final String? description;
  final String? phone;
  final String? address;
  @JsonKey(name: 'location_url')
  final String? locationUrl;
  @JsonKey(name: 'is_open')
  final bool isOpen;
  @JsonKey(name: 'min_order_amount')
  final num minOrderAmount;
  @JsonKey(name: 'payment_settings')
  final String paymentSettings;
  @JsonKey(name: 'payment_account_type')
  final String paymentAccountType;
  final dynamic status;
  @JsonKey(name: 'is_order_locked')
  final bool isOrderLocked;
  @JsonKey(name: 'allowed_methods')
  final List<dynamic> allowedMethods;

  Map<String, Object?> toJson() => _$Data48ToJson(this);
}
