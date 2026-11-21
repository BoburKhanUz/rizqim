// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data339.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data339 _$Data339FromJson(Map<String, dynamic> json) => Data339(
  releases: (json['releases'] as List<dynamic>)
      .map((e) => Releases.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data339ToJson(Data339 instance) => <String, dynamic>{
  'releases': instance.releases,
};
