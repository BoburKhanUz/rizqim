// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers6 _$Modifiers6FromJson(Map<String, dynamic> json) => Modifiers6(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  groupName: json['group_name'] as String?,
  priceDelta: json['price_delta'] as String?,
);

Map<String, dynamic> _$Modifiers6ToJson(Modifiers6 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
