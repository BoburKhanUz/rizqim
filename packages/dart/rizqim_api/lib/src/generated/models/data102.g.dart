// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data102.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data102 _$Data102FromJson(Map<String, dynamic> json) => Data102(
  driver: json['driver'] as String?,
  tin: json['tin'] as String?,
  tinSet: json['tin_set'] as bool,
  tills: (json['tills'] as List<dynamic>)
      .map((e) => Tills.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data102ToJson(Data102 instance) => <String, dynamic>{
  'driver': instance.driver,
  'tin': instance.tin,
  'tin_set': instance.tinSet,
  'tills': instance.tills,
};
