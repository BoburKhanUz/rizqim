// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers35.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers35 _$Modifiers35FromJson(Map<String, dynamic> json) => Modifiers35(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  groupName: json['group_name'] as String?,
  priceDelta: json['price_delta'] as String?,
);

Map<String, dynamic> _$Modifiers35ToJson(Modifiers35 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
