// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data283.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data283 _$Data283FromJson(Map<String, dynamic> json) => Data283(
  members: (json['members'] as List<dynamic>)
      .map((e) => Members.fromJson(e as Map<String, dynamic>))
      .toList(),
  activeCount: (json['active_count'] as num).toInt(),
);

Map<String, dynamic> _$Data283ToJson(Data283 instance) => <String, dynamic>{
  'members': instance.members,
  'active_count': instance.activeCount,
};
