// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data103.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data103 _$Data103FromJson(Map<String, dynamic> json) => Data103(
  driver: json['driver'] as String?,
  tin: json['tin'] as String?,
  tinSet: json['tin_set'] as bool,
  tills: (json['tills'] as List<dynamic>)
      .map((e) => Tills.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data103ToJson(Data103 instance) => <String, dynamic>{
  'driver': instance.driver,
  'tin': instance.tin,
  'tin_set': instance.tinSet,
  'tills': instance.tills,
};
