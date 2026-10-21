// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'modifiers17.dart';

part 'items36.g.dart';

@JsonSerializable()
class Items36 {
  const Items36({
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
    this.sentAt,
    this.dueAt,
    this.seatNo,
    this.note,
    this.voidedAt,
    this.voidReason,
    this.unitPrice,
    this.modifiersTotal,
    this.totalPrice,
  });

  factory Items36.fromJson(Map<String, Object?> json) =>
      _$Items36FromJson(json);

  final int id;
  @JsonKey(name: 'product_name')
  final String productName;
  @JsonKey(name: 'variant_name')
  final String? variantName;
  final num quantity;
  final String unit;
  @JsonKey(name: 'quantity_label')
  final String quantityLabel;
  final List<Modifiers17> modifiers;
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
  @JsonKey(name: 'unit_price')
  final String? unitPrice;
  @JsonKey(name: 'modifiers_total')
  final String? modifiersTotal;
  @JsonKey(name: 'total_price')
  final String? totalPrice;

  Map<String, Object?> toJson() => _$Items36ToJson(this);
}
