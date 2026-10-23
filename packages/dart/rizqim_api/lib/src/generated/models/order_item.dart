// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'fire_mode.dart';
import 'kitchen_item_status.dart';
import 'menu_batch.dart';
import 'menu_unit.dart';
import 'order.dart';
import 'product_variant.dart';

part 'order_item.g.dart';

@JsonSerializable()
class OrderItem {
  const OrderItem({
    required this.unit,
    required this.orderId,
    required this.productVariantId,
    required this.productName,
    required this.variantName,
    required this.quantity,
    required this.unitPrice,
    required this.totalPrice,
    required this.createdAt,
    required this.updatedAt,
    required this.deletedAt,
    required this.id,
    required this.modifiers,
    required this.modifiersTotal,
    required this.batchId,
    required this.batchQuantity,
    required this.kitchenStatus,
    required this.readyAt,
    required this.roundNo,
    required this.fireMode,
    required this.stationId,
    required this.priceSource,
    required this.dueAt,
    required this.addedBy,
    required this.voidedAt,
    required this.voidedBy,
    required this.voidReason,
    required this.seatNo,
    required this.note,
    required this.clientLineId,
    required this.sentAt,
    this.order,
    this.productVariant,
    this.batch,
  });

  factory OrderItem.fromJson(Map<String, Object?> json) =>
      _$OrderItemFromJson(json);

  final int id;
  @JsonKey(name: 'order_id')
  final int orderId;
  @JsonKey(name: 'product_variant_id')
  final int? productVariantId;
  @JsonKey(name: 'product_name')
  final String productName;
  @JsonKey(name: 'variant_name')
  final String? variantName;
  final num quantity;
  @JsonKey(name: 'unit_price')
  final String unitPrice;
  @JsonKey(name: 'total_price')
  final String totalPrice;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  @JsonKey(name: 'deleted_at')
  final DateTime? deletedAt;
  final MenuUnit unit;
  final List<Map<String, dynamic>>? modifiers;
  @JsonKey(name: 'modifiers_total')
  final String modifiersTotal;
  @JsonKey(name: 'batch_id')
  final int? batchId;
  @JsonKey(name: 'batch_quantity')
  final String? batchQuantity;
  @JsonKey(name: 'kitchen_status')
  final KitchenItemStatus kitchenStatus;
  @JsonKey(name: 'ready_at')
  final DateTime? readyAt;
  @JsonKey(name: 'round_no')
  final int? roundNo;
  @JsonKey(name: 'fire_mode')
  final FireMode? fireMode;
  @JsonKey(name: 'station_id')
  final int? stationId;
  @JsonKey(name: 'sent_at')
  final DateTime? sentAt;
  @JsonKey(name: 'due_at')
  final DateTime? dueAt;
  @JsonKey(name: 'added_by')
  final dynamic addedBy;
  @JsonKey(name: 'voided_at')
  final DateTime? voidedAt;
  @JsonKey(name: 'voided_by')
  final dynamic voidedBy;
  @JsonKey(name: 'void_reason')
  final String? voidReason;
  @JsonKey(name: 'seat_no')
  final int? seatNo;
  final String? note;
  @JsonKey(name: 'client_line_id')
  final String? clientLineId;
  @JsonKey(name: 'price_source')
  final String? priceSource;
  final Order? order;
  @JsonKey(name: 'product_variant')
  final ProductVariant? productVariant;
  final MenuBatch? batch;

  Map<String, Object?> toJson() => _$OrderItemToJson(this);
}
