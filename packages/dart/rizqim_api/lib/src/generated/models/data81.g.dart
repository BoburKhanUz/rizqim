// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data81.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data81 _$Data81FromJson(Map<String, dynamic> json) => Data81(
  employee: User.fromJson(json['employee'] as Map<String, dynamic>),
  inviteLink: json['invite_link'] as String,
);

Map<String, dynamic> _$Data81ToJson(Data81 instance) => <String, dynamic>{
  'employee': instance.employee,
  'invite_link': instance.inviteLink,
};
