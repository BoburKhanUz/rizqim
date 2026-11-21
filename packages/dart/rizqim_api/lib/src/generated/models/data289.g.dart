// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data289.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data289 _$Data289FromJson(Map<String, dynamic> json) => Data289(
  created: (json['created'] as num).toInt(),
  members: (json['members'] as List<dynamic>)
      .map((e) => Members2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data289ToJson(Data289 instance) => <String, dynamic>{
  'created': instance.created,
  'members': instance.members,
};
