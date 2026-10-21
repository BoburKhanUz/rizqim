// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'booking.dart';
import 'menu_unit.dart';
import 'product_variant.dart';

part 'booking_item.g.dart';

@JsonSerializable()
class BookingItem {
  const BookingItem({
    required this.id,
    required this.bookingId,
    required this.productVariantId,
    required this.productName,
    required this.variantName,
    required this.unit,
    required this.quantity,
    required this.modifiers,
    required this.unitPrice,
    required this.note,
    required this.dueAt,
    required this.createdBy,
    required this.createdAt,
    required this.updatedAt,
    this.booking,
    this.variant,
  });

  factory BookingItem.fromJson(Map<String, Object?> json) =>
      _$BookingItemFromJson(json);

  final int id;
  @JsonKey(name: 'booking_id')
  final int bookingId;
  @JsonKey(name: 'product_variant_id')
  final int productVariantId;
  @JsonKey(name: 'product_name')
  final String productName;
  @JsonKey(name: 'variant_name')
  final String? variantName;
  final MenuUnit unit;
  final String quantity;
  final List<Map<String, dynamic>>? modifiers;
  @JsonKey(name: 'unit_price')
  final String unitPrice;
  final String? note;
  @JsonKey(name: 'due_at')
  final DateTime? dueAt;
  @JsonKey(name: 'created_by')
  final int? createdBy;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  final Booking? booking;
  final ProductVariant? variant;

  Map<String, Object?> toJson() => _$BookingItemToJson(this);
}
