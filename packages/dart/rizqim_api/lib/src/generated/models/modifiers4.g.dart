// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers4 _$Modifiers4FromJson(Map<String, dynamic> json) => Modifiers4(
  modifierId: (json['modifier_id'] as num).toInt(),
  quantity: (json['quantity'] as num?)?.toInt(),
);

Map<String, dynamic> _$Modifiers4ToJson(Modifiers4 instance) =>
    <String, dynamic>{
      'modifier_id': instance.modifierId,
      'quantity': instance.quantity,
    };
