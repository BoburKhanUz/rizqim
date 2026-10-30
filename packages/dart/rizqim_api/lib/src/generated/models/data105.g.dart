// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data105.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data105 _$Data105FromJson(Map<String, dynamic> json) => Data105(
  branchId: (json['branch_id'] as num).toInt(),
  enabled: json['enabled'] as bool,
  limits: Limits.fromJson(json['limits'] as Map<String, dynamic>),
  stations: (json['stations'] as List<dynamic>)
      .map((e) => Stations.fromJson(e as Map<String, dynamic>))
      .toList(),
  products: (json['products'] as List<dynamic>)
      .map((e) => Products4.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data105ToJson(Data105 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'enabled': instance.enabled,
  'limits': instance.limits,
  'stations': instance.stations,
  'products': instance.products,
};
