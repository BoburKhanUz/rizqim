// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers20.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers20 _$Modifiers20FromJson(Map<String, dynamic> json) => Modifiers20(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  groupName: json['group_name'] as String?,
  priceDelta: json['price_delta'] as String?,
);

Map<String, dynamic> _$Modifiers20ToJson(Modifiers20 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
