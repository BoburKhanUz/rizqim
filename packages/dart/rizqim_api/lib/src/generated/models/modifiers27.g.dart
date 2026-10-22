// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers27.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers27 _$Modifiers27FromJson(Map<String, dynamic> json) => Modifiers27(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  groupName: json['group_name'] as String?,
  priceDelta: json['price_delta'] as String?,
);

Map<String, dynamic> _$Modifiers27ToJson(Modifiers27 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
