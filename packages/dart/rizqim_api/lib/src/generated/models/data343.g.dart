// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data343.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data343 _$Data343FromJson(Map<String, dynamic> json) => Data343(
  releases: (json['releases'] as List<dynamic>)
      .map((e) => Releases.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data343ToJson(Data343 instance) => <String, dynamic>{
  'releases': instance.releases,
};
