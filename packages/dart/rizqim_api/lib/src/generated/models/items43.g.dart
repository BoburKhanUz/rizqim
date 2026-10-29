// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'items43.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Items43 _$Items43FromJson(Map<String, dynamic> json) => Items43(
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
  courseNo: (json['course_no'] as num?)?.toInt(),
  awaitingWeight: json['awaiting_weight'] as bool?,
);

Map<String, dynamic> _$Items43ToJson(Items43 instance) => <String, dynamic>{
  'variant_id': instance.variantId,
  'quantity': instance.quantity,
  'modifiers': instance.modifiers,
  'note': instance.note,
  'seat_no': instance.seatNo,
  'fire_mode': instance.fireMode,
  'due_at': instance.dueAt?.toIso8601String(),
  'course_no': instance.courseNo,
  'awaiting_weight': instance.awaitingWeight,
};
