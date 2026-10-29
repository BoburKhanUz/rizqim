// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'order_item.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

OrderItem _$OrderItemFromJson(Map<String, dynamic> json) => OrderItem(
  modifiers: (json['modifiers'] as List<dynamic>?)
      ?.map((e) => e as Map<String, dynamic>)
      .toList(),
  orderId: (json['order_id'] as num).toInt(),
  productVariantId: (json['product_variant_id'] as num?)?.toInt(),
  productName: json['product_name'] as String,
  variantName: json['variant_name'] as String?,
  quantity: json['quantity'] as num,
  unitPrice: json['unit_price'] as String,
  totalPrice: json['total_price'] as String,
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  updatedAt: json['updated_at'] == null
      ? null
      : DateTime.parse(json['updated_at'] as String),
  deletedAt: json['deleted_at'] == null
      ? null
      : DateTime.parse(json['deleted_at'] as String),
  unit: MenuUnit.fromJson(json['unit'] as String),
  id: (json['id'] as num).toInt(),
  modifiersTotal: json['modifiers_total'] as String,
  batchId: (json['batch_id'] as num?)?.toInt(),
  batchQuantity: json['batch_quantity'] as String?,
  kitchenStatus: KitchenItemStatus.fromJson(json['kitchen_status'] as String),
  readyAt: json['ready_at'] == null
      ? null
      : DateTime.parse(json['ready_at'] as String),
  roundNo: (json['round_no'] as num?)?.toInt(),
  fireMode: json['fire_mode'] == null
      ? null
      : FireMode.fromJson(json['fire_mode'] as String?),
  stationId: (json['station_id'] as num?)?.toInt(),
  sentAt: json['sent_at'] == null
      ? null
      : DateTime.parse(json['sent_at'] as String),
  setParts: (json['set_parts'] as List<dynamic>?)
      ?.map((e) => e as Map<String, dynamic>)
      .toList(),
  addedBy: json['added_by'],
  voidedAt: json['voided_at'] == null
      ? null
      : DateTime.parse(json['voided_at'] as String),
  voidedBy: json['voided_by'],
  voidReason: json['void_reason'] as String?,
  seatNo: (json['seat_no'] as num?)?.toInt(),
  note: json['note'] as String?,
  clientLineId: json['client_line_id'] as String?,
  priceSource: json['price_source'] as String?,
  dueAt: json['due_at'] == null
      ? null
      : DateTime.parse(json['due_at'] as String),
  order: json['order'] == null
      ? null
      : Order.fromJson(json['order'] as Map<String, dynamic>),
  productVariant: json['product_variant'] == null
      ? null
      : ProductVariant.fromJson(
          json['product_variant'] as Map<String, dynamic>,
        ),
  batch: json['batch'] == null
      ? null
      : MenuBatch.fromJson(json['batch'] as Map<String, dynamic>),
);

Map<String, dynamic> _$OrderItemToJson(OrderItem instance) => <String, dynamic>{
  'id': instance.id,
  'order_id': instance.orderId,
  'product_variant_id': instance.productVariantId,
  'product_name': instance.productName,
  'variant_name': instance.variantName,
  'quantity': instance.quantity,
  'unit_price': instance.unitPrice,
  'total_price': instance.totalPrice,
  'created_at': instance.createdAt?.toIso8601String(),
  'updated_at': instance.updatedAt?.toIso8601String(),
  'deleted_at': instance.deletedAt?.toIso8601String(),
  'unit': instance.unit,
  'modifiers': instance.modifiers,
  'modifiers_total': instance.modifiersTotal,
  'batch_id': instance.batchId,
  'batch_quantity': instance.batchQuantity,
  'kitchen_status': instance.kitchenStatus,
  'ready_at': instance.readyAt?.toIso8601String(),
  'round_no': instance.roundNo,
  'fire_mode': instance.fireMode,
  'station_id': instance.stationId,
  'sent_at': instance.sentAt?.toIso8601String(),
  'due_at': instance.dueAt?.toIso8601String(),
  'added_by': instance.addedBy,
  'voided_at': instance.voidedAt?.toIso8601String(),
  'voided_by': instance.voidedBy,
  'void_reason': instance.voidReason,
  'seat_no': instance.seatNo,
  'note': instance.note,
  'client_line_id': instance.clientLineId,
  'price_source': instance.priceSource,
  'set_parts': instance.setParts,
  'order': instance.order,
  'product_variant': instance.productVariant,
  'batch': instance.batch,
};
