// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data288.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data288 _$Data288FromJson(Map<String, dynamic> json) => Data288(
  created: (json['created'] as num).toInt(),
  members: (json['members'] as List<dynamic>)
      .map((e) => Members2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data288ToJson(Data288 instance) => <String, dynamic>{
  'created': instance.created,
  'members': instance.members,
};
