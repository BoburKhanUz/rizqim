// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data85.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data85 _$Data85FromJson(Map<String, dynamic> json) => Data85(
  employee: User.fromJson(json['employee'] as Map<String, dynamic>),
  inviteLink: json['invite_link'] as String,
);

Map<String, dynamic> _$Data85ToJson(Data85 instance) => <String, dynamic>{
  'employee': instance.employee,
  'invite_link': instance.inviteLink,
};
