// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'modifiers39.dart';

part 'items61.g.dart';

@JsonSerializable()
class Items61 {
  const Items61({
    required this.id,
    required this.productName,
    required this.variantName,
    required this.quantity,
    required this.unit,
    required this.quantityLabel,
    required this.modifiers,
    required this.kitchenStatus,
    required this.readyAt,
    this.roundNo,
    this.fireMode,
    this.stationId,
    this.productId,
    this.categoryId,
    this.sentAt,
    this.dueAt,
    this.seatNo,
    this.note,
    this.voidedAt,
    this.voidReason,
    this.courseNo,
    this.weightStatus,
    this.waitMinutes,
    this.targetSeconds,
    this.lateValue,
    this.unitPrice,
    this.modifiersTotal,
    this.totalPrice,
  });

  factory Items61.fromJson(Map<String, Object?> json) =>
      _$Items61FromJson(json);

  final int id;
  @JsonKey(name: 'product_name')
  final String productName;
  @JsonKey(name: 'variant_name')
  final String? variantName;
  final num quantity;
  final String unit;
  @JsonKey(name: 'quantity_label')
  final String quantityLabel;
  final List<Modifiers39> modifiers;
  @JsonKey(name: 'kitchen_status')
  final String kitchenStatus;
  @JsonKey(name: 'ready_at')
  final String? readyAt;
  @JsonKey(name: 'round_no')
  final int? roundNo;
  @JsonKey(name: 'fire_mode')
  final String? fireMode;
  @JsonKey(name: 'station_id')
  final int? stationId;
  @JsonKey(name: 'product_id')
  final int? productId;
  @JsonKey(name: 'category_id')
  final int? categoryId;
  @JsonKey(name: 'sent_at')
  final String? sentAt;
  @JsonKey(name: 'due_at')
  final String? dueAt;
  @JsonKey(name: 'seat_no')
  final int? seatNo;
  final String? note;
  @JsonKey(name: 'voided_at')
  final String? voidedAt;
  @JsonKey(name: 'void_reason')
  final String? voidReason;
  @JsonKey(name: 'course_no')
  final int? courseNo;
  @JsonKey(name: 'weight_status')
  final String? weightStatus;
  @JsonKey(name: 'wait_minutes')
  final int? waitMinutes;
  @JsonKey(name: 'target_seconds')
  final int? targetSeconds;

  /// The name has been replaced because it contains a keyword. Original name: `late`.
  @JsonKey(name: 'late')
  final bool? lateValue;
  @JsonKey(name: 'unit_price')
  final String? unitPrice;
  @JsonKey(name: 'modifiers_total')
  final String? modifiersTotal;
  @JsonKey(name: 'total_price')
  final String? totalPrice;

  Map<String, Object?> toJson() => _$Items61ToJson(this);
}
