// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers42.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers42 _$Modifiers42FromJson(Map<String, dynamic> json) => Modifiers42(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  groupName: json['group_name'] as String?,
  priceDelta: json['price_delta'] as String?,
);

Map<String, dynamic> _$Modifiers42ToJson(Modifiers42 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
