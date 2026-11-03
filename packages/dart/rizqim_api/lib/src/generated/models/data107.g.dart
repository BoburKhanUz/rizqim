// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data107.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data107 _$Data107FromJson(Map<String, dynamic> json) => Data107(
  branchId: (json['branch_id'] as num).toInt(),
  enabled: json['enabled'] as bool,
  expoScope: json['expo_scope'] as String,
  limits: Limits3.fromJson(json['limits'] as Map<String, dynamic>),
  stations: (json['stations'] as List<dynamic>)
      .map((e) => Stations3.fromJson(e as Map<String, dynamic>))
      .toList(),
  products: (json['products'] as List<dynamic>)
      .map((e) => Products6.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data107ToJson(Data107 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'enabled': instance.enabled,
  'expo_scope': instance.expoScope,
  'limits': instance.limits,
  'stations': instance.stations,
  'products': instance.products,
};
