// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'items40.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Items40 _$Items40FromJson(Map<String, dynamic> json) => Items40(
  variantId: json['variant_id'] as String,
  quantity: json['quantity'] as num,
  modifiers: (json['modifiers'] as List<dynamic>?)
      ?.map((e) => e as String)
      .toList(),
  note: json['note'] as String?,
  seatNo: (json['seat_no'] as num?)?.toInt(),
  fireMode: json['fire_mode'] == null
      ? null
      : FireMode.fromJson(json['fire_mode'] as String?),
  dueAt: json['due_at'] == null
      ? null
      : DateTime.parse(json['due_at'] as String),
);

Map<String, dynamic> _$Items40ToJson(Items40 instance) => <String, dynamic>{
  'variant_id': instance.variantId,
  'quantity': instance.quantity,
  'modifiers': instance.modifiers,
  'note': instance.note,
  'seat_no': instance.seatNo,
  'fire_mode': instance.fireMode,
  'due_at': instance.dueAt?.toIso8601String(),
};
