// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers5 _$Modifiers5FromJson(Map<String, dynamic> json) => Modifiers5(
  modifierId: (json['modifier_id'] as num).toInt(),
  quantity: (json['quantity'] as num?)?.toInt(),
);

Map<String, dynamic> _$Modifiers5ToJson(Modifiers5 instance) =>
    <String, dynamic>{
      'modifier_id': instance.modifierId,
      'quantity': instance.quantity,
    };
