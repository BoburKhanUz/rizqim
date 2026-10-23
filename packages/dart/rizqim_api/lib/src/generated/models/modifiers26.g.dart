// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers26.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers26 _$Modifiers26FromJson(Map<String, dynamic> json) => Modifiers26(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  groupName: json['group_name'] as String?,
  priceDelta: json['price_delta'] as String?,
);

Map<String, dynamic> _$Modifiers26ToJson(Modifiers26 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
