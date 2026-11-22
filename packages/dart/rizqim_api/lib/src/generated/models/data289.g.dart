// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data289.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data289 _$Data289FromJson(Map<String, dynamic> json) => Data289(
  members: (json['members'] as List<dynamic>)
      .map((e) => Members.fromJson(e as Map<String, dynamic>))
      .toList(),
  activeCount: (json['active_count'] as num).toInt(),
);

Map<String, dynamic> _$Data289ToJson(Data289 instance) => <String, dynamic>{
  'members': instance.members,
  'active_count': instance.activeCount,
};
