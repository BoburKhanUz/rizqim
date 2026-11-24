// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data143.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data143 _$Data143FromJson(Map<String, dynamic> json) => Data143(
  packages: (json['packages'] as List<dynamic>)
      .map((e) => Packages.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data143ToJson(Data143 instance) => <String, dynamic>{
  'packages': instance.packages,
};
