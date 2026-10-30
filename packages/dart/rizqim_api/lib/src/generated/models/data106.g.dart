// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data106.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data106 _$Data106FromJson(Map<String, dynamic> json) => Data106(
  branchId: (json['branch_id'] as num).toInt(),
  enabled: json['enabled'] as bool,
  limits: Limits2.fromJson(json['limits'] as Map<String, dynamic>),
  stations: (json['stations'] as List<dynamic>)
      .map((e) => Stations2.fromJson(e as Map<String, dynamic>))
      .toList(),
  products: (json['products'] as List<dynamic>)
      .map((e) => Products5.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data106ToJson(Data106 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'enabled': instance.enabled,
  'limits': instance.limits,
  'stations': instance.stations,
  'products': instance.products,
};
