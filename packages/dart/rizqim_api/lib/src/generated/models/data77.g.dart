// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data77.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data77 _$Data77FromJson(Map<String, dynamic> json) => Data77(
  employee: User.fromJson(json['employee'] as Map<String, dynamic>),
  inviteLink: json['invite_link'] as String,
);

Map<String, dynamic> _$Data77ToJson(Data77 instance) => <String, dynamic>{
  'employee': instance.employee,
  'invite_link': instance.inviteLink,
};
