// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data341.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data341 _$Data341FromJson(Map<String, dynamic> json) => Data341(
  releases: (json['releases'] as List<dynamic>)
      .map((e) => Releases.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data341ToJson(Data341 instance) => <String, dynamic>{
  'releases': instance.releases,
};
