// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers9.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers9 _$Modifiers9FromJson(Map<String, dynamic> json) => Modifiers9(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  groupName: json['group_name'] as String?,
  priceDelta: json['price_delta'] as String?,
);

Map<String, dynamic> _$Modifiers9ToJson(Modifiers9 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
