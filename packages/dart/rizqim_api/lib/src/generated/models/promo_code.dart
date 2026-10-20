// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'order.dart';
import 'store.dart';

part 'promo_code.g.dart';

@JsonSerializable()
class PromoCode {
  const PromoCode({
    required this.id,
    required this.code,
    required this.type,
    required this.value,
    required this.minOrderAmount,
    required this.maxDiscountAmount,
    required this.isActive,
    required this.expiresAt,
    required this.storeId,
    required this.createdAt,
    required this.updatedAt,
    required this.usageLimit,
    required this.perUserLimit,
    this.store,
    this.orders,
  });

  factory PromoCode.fromJson(Map<String, Object?> json) =>
      _$PromoCodeFromJson(json);

  final int id;
  final String code;
  final String type;
  final num value;
  @JsonKey(name: 'min_order_amount')
  final String minOrderAmount;
  @JsonKey(name: 'max_discount_amount')
  final String? maxDiscountAmount;
  @JsonKey(name: 'is_active')
  final bool isActive;
  @JsonKey(name: 'expires_at')
  final DateTime? expiresAt;
  @JsonKey(name: 'store_id')
  final int? storeId;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  @JsonKey(name: 'usage_limit')
  final int? usageLimit;
  @JsonKey(name: 'per_user_limit')
  final int? perUserLimit;
  final Store? store;
  final List<Order>? orders;

  Map<String, Object?> toJson() => _$PromoCodeToJson(this);
}
