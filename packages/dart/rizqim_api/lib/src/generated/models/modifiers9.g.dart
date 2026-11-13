// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers9.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers9 _$Modifiers9FromJson(Map<String, dynamic> json) => Modifiers9(
  modifierId: (json['modifier_id'] as num).toInt(),
  quantity: (json['quantity'] as num?)?.toInt(),
);

Map<String, dynamic> _$Modifiers9ToJson(Modifiers9 instance) =>
    <String, dynamic>{
      'modifier_id': instance.modifierId,
      'quantity': instance.quantity,
    };
