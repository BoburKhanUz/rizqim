// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data112.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data112 _$Data112FromJson(Map<String, dynamic> json) => Data112(
  branchId: (json['branch_id'] as num).toInt(),
  enabled: json['enabled'] as bool,
  expoScope: json['expo_scope'] as String,
  limits: Limits2.fromJson(json['limits'] as Map<String, dynamic>),
  stations: (json['stations'] as List<dynamic>)
      .map((e) => Stations2.fromJson(e as Map<String, dynamic>))
      .toList(),
  products: (json['products'] as List<dynamic>)
      .map((e) => Products5.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data112ToJson(Data112 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'enabled': instance.enabled,
  'expo_scope': instance.expoScope,
  'limits': instance.limits,
  'stations': instance.stations,
  'products': instance.products,
};
