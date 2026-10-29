// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data130.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data130 _$Data130FromJson(Map<String, dynamic> json) => Data130(
  staff: Staff.fromJson(json['staff'] as Map<String, dynamic>),
  inviteLink: json['invite_link'] as String,
);

Map<String, dynamic> _$Data130ToJson(Data130 instance) => <String, dynamic>{
  'staff': instance.staff,
  'invite_link': instance.inviteLink,
};
