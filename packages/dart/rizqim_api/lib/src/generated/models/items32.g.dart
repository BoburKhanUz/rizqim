// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'items32.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Items32 _$Items32FromJson(Map<String, dynamic> json) => Items32(
  id: (json['id'] as num).toInt(),
  roundNo: (json['round_no'] as num?)?.toInt(),
  productVariantId: (json['product_variant_id'] as num?)?.toInt(),
  productName: json['product_name'] as String,
  variantName: json['variant_name'] as String?,
  quantity: json['quantity'] as num,
  unit: json['unit'] as String,
  quantityLabel: json['quantity_label'] as String,
  unitPrice: json['unit_price'] as String,
  modifiers: (json['modifiers'] as List<dynamic>)
      .map((e) => Modifiers14.fromJson(e as Map<String, dynamic>))
      .toList(),
  modifiersTotal: json['modifiers_total'] as String,
  totalPrice: json['total_price'] as String,
  note: json['note'] as String?,
  seatNo: (json['seat_no'] as num?)?.toInt(),
  fireMode: json['fire_mode'] as String?,
  dueAt: json['due_at'] as String?,
  kitchenStatus: json['kitchen_status'] as String,
  stationId: (json['station_id'] as num?)?.toInt(),
  sentAt: json['sent_at'] as String?,
  readyAt: json['ready_at'] as String?,
  addedBy: json['added_by'] == null
      ? null
      : AddedBy7.fromJson(json['added_by'] as Map<String, dynamic>),
  addedAt: json['added_at'] as String?,
  voidedAt: json['voided_at'] as String?,
  voidedBy: json['voided_by'] == null
      ? null
      : VoidedBy7.fromJson(json['voided_by'] as Map<String, dynamic>),
  voidReason: json['void_reason'] as String?,
  courseNo: (json['course_no'] as num?)?.toInt(),
  weightStatus: json['weight_status'] as String?,
  weightBasis: json['weight_basis'] as String?,
  weighedBy: json['weighed_by'] == null
      ? null
      : WeighedBy7.fromJson(json['weighed_by'] as Map<String, dynamic>),
  weighedAt: json['weighed_at'] as String?,
);

Map<String, dynamic> _$Items32ToJson(Items32 instance) => <String, dynamic>{
  'id': instance.id,
  'round_no': instance.roundNo,
  'product_variant_id': instance.productVariantId,
  'product_name': instance.productName,
  'variant_name': instance.variantName,
  'quantity': instance.quantity,
  'unit': instance.unit,
  'quantity_label': instance.quantityLabel,
  'unit_price': instance.unitPrice,
  'modifiers': instance.modifiers,
  'modifiers_total': instance.modifiersTotal,
  'total_price': instance.totalPrice,
  'note': instance.note,
  'seat_no': instance.seatNo,
  'fire_mode': instance.fireMode,
  'due_at': instance.dueAt,
  'kitchen_status': instance.kitchenStatus,
  'station_id': instance.stationId,
  'sent_at': instance.sentAt,
  'ready_at': instance.readyAt,
  'added_by': instance.addedBy,
  'added_at': instance.addedAt,
  'voided_at': instance.voidedAt,
  'voided_by': instance.voidedBy,
  'void_reason': instance.voidReason,
  'course_no': instance.courseNo,
  'weight_status': instance.weightStatus,
  'weight_basis': instance.weightBasis,
  'weighed_by': instance.weighedBy,
  'weighed_at': instance.weighedAt,
};
