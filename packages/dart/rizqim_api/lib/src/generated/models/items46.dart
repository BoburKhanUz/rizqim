// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'modifiers30.dart';
import 'added_by19.dart';
import 'voided_by19.dart';
import 'weighed_by19.dart';

part 'items46.g.dart';

@JsonSerializable()
class Items46 {
  const Items46({
    required this.id,
    required this.roundNo,
    required this.productVariantId,
    required this.productName,
    required this.variantName,
    required this.quantity,
    required this.unit,
    required this.quantityLabel,
    required this.unitPrice,
    required this.modifiers,
    required this.modifiersTotal,
    required this.totalPrice,
    required this.note,
    required this.seatNo,
    required this.fireMode,
    required this.dueAt,
    required this.kitchenStatus,
    required this.stationId,
    required this.sentAt,
    required this.readyAt,
    required this.addedBy,
    required this.addedAt,
    required this.voidedAt,
    required this.voidedBy,
    required this.voidReason,
    required this.courseNo,
    required this.weightStatus,
    required this.weightBasis,
    required this.weighedBy,
    required this.weighedAt,
  });

  factory Items46.fromJson(Map<String, Object?> json) =>
      _$Items46FromJson(json);

  final int id;
  @JsonKey(name: 'round_no')
  final int? roundNo;
  @JsonKey(name: 'product_variant_id')
  final int? productVariantId;
  @JsonKey(name: 'product_name')
  final String productName;
  @JsonKey(name: 'variant_name')
  final String? variantName;
  final num quantity;
  final String unit;
  @JsonKey(name: 'quantity_label')
  final String quantityLabel;
  @JsonKey(name: 'unit_price')
  final String unitPrice;
  final List<Modifiers30> modifiers;
  @JsonKey(name: 'modifiers_total')
  final String modifiersTotal;
  @JsonKey(name: 'total_price')
  final String totalPrice;
  final String? note;
  @JsonKey(name: 'seat_no')
  final int? seatNo;
  @JsonKey(name: 'fire_mode')
  final String? fireMode;
  @JsonKey(name: 'due_at')
  final String? dueAt;
  @JsonKey(name: 'kitchen_status')
  final String kitchenStatus;
  @JsonKey(name: 'station_id')
  final int? stationId;
  @JsonKey(name: 'sent_at')
  final String? sentAt;
  @JsonKey(name: 'ready_at')
  final String? readyAt;
  @JsonKey(name: 'added_by')
  final AddedBy19? addedBy;
  @JsonKey(name: 'added_at')
  final String? addedAt;
  @JsonKey(name: 'voided_at')
  final String? voidedAt;
  @JsonKey(name: 'voided_by')
  final VoidedBy19? voidedBy;
  @JsonKey(name: 'void_reason')
  final String? voidReason;
  @JsonKey(name: 'course_no')
  final int? courseNo;
  @JsonKey(name: 'weight_status')
  final String? weightStatus;
  @JsonKey(name: 'weight_basis')
  final String? weightBasis;
  @JsonKey(name: 'weighed_by')
  final WeighedBy19? weighedBy;
  @JsonKey(name: 'weighed_at')
  final String? weighedAt;

  Map<String, Object?> toJson() => _$Items46ToJson(this);
}
