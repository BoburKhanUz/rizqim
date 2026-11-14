// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data84.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data84 _$Data84FromJson(Map<String, dynamic> json) => Data84(
  employee: User.fromJson(json['employee'] as Map<String, dynamic>),
  inviteLink: json['invite_link'] as String,
);

Map<String, dynamic> _$Data84ToJson(Data84 instance) => <String, dynamic>{
  'employee': instance.employee,
  'invite_link': instance.inviteLink,
};
