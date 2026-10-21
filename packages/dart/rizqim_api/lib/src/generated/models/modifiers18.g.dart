// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers18.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers18 _$Modifiers18FromJson(Map<String, dynamic> json) => Modifiers18(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  groupName: json['group_name'] as String?,
  priceDelta: json['price_delta'] as String?,
);

Map<String, dynamic> _$Modifiers18ToJson(Modifiers18 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
