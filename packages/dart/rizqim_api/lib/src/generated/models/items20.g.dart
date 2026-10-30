// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'items20.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Items20 _$Items20FromJson(Map<String, dynamic> json) => Items20(
  id: (json['id'] as num).toInt(),
  productName: json['product_name'] as String,
  variantName: json['variant_name'] as String?,
  quantity: json['quantity'] as num,
  unit: json['unit'] as String,
  quantityLabel: json['quantity_label'] as String,
  modifiers: (json['modifiers'] as List<dynamic>)
      .map((e) => Modifiers9.fromJson(e as Map<String, dynamic>))
      .toList(),
  kitchenStatus: json['kitchen_status'] as String,
  readyAt: json['ready_at'] as String?,
  roundNo: (json['round_no'] as num?)?.toInt(),
  fireMode: json['fire_mode'] as String?,
  stationId: (json['station_id'] as num?)?.toInt(),
  productId: (json['product_id'] as num?)?.toInt(),
  categoryId: (json['category_id'] as num?)?.toInt(),
  sentAt: json['sent_at'] as String?,
  dueAt: json['due_at'] as String?,
  seatNo: (json['seat_no'] as num?)?.toInt(),
  note: json['note'] as String?,
  voidedAt: json['voided_at'] as String?,
  voidReason: json['void_reason'] as String?,
  courseNo: (json['course_no'] as num?)?.toInt(),
  weightStatus: json['weight_status'] as String?,
  waitMinutes: (json['wait_minutes'] as num?)?.toInt(),
  targetSeconds: (json['target_seconds'] as num?)?.toInt(),
  lateValue: json['late'] as bool?,
  unitPrice: json['unit_price'] as String?,
  modifiersTotal: json['modifiers_total'] as String?,
  totalPrice: json['total_price'] as String?,
);

Map<String, dynamic> _$Items20ToJson(Items20 instance) => <String, dynamic>{
  'id': instance.id,
  'product_name': instance.productName,
  'variant_name': instance.variantName,
  'quantity': instance.quantity,
  'unit': instance.unit,
  'quantity_label': instance.quantityLabel,
  'modifiers': instance.modifiers,
  'kitchen_status': instance.kitchenStatus,
  'ready_at': instance.readyAt,
  'round_no': instance.roundNo,
  'fire_mode': instance.fireMode,
  'station_id': instance.stationId,
  'product_id': instance.productId,
  'category_id': instance.categoryId,
  'sent_at': instance.sentAt,
  'due_at': instance.dueAt,
  'seat_no': instance.seatNo,
  'note': instance.note,
  'voided_at': instance.voidedAt,
  'void_reason': instance.voidReason,
  'course_no': instance.courseNo,
  'weight_status': instance.weightStatus,
  'wait_minutes': instance.waitMinutes,
  'target_seconds': instance.targetSeconds,
  'late': instance.lateValue,
  'unit_price': instance.unitPrice,
  'modifiers_total': instance.modifiersTotal,
  'total_price': instance.totalPrice,
};
