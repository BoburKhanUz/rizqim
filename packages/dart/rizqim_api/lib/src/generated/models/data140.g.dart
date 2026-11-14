// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data140.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data140 _$Data140FromJson(Map<String, dynamic> json) => Data140(
  packages: (json['packages'] as List<dynamic>)
      .map((e) => Packages.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data140ToJson(Data140 instance) => <String, dynamic>{
  'packages': instance.packages,
};
