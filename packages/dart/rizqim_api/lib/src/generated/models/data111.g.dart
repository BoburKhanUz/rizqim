// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data111.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data111 _$Data111FromJson(Map<String, dynamic> json) => Data111(
  staff: Staff.fromJson(json['staff'] as Map<String, dynamic>),
  inviteLink: json['invite_link'] as String,
);

Map<String, dynamic> _$Data111ToJson(Data111 instance) => <String, dynamic>{
  'staff': instance.staff,
  'invite_link': instance.inviteLink,
};
