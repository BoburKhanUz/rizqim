// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data342.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data342 _$Data342FromJson(Map<String, dynamic> json) => Data342(
  releases: (json['releases'] as List<dynamic>)
      .map((e) => Releases.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data342ToJson(Data342 instance) => <String, dynamic>{
  'releases': instance.releases,
};
