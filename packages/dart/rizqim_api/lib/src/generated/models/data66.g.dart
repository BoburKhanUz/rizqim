// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data66.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data66 _$Data66FromJson(Map<String, dynamic> json) => Data66(
  employee: User.fromJson(json['employee'] as Map<String, dynamic>),
  inviteLink: json['invite_link'] as String,
);

Map<String, dynamic> _$Data66ToJson(Data66 instance) => <String, dynamic>{
  'employee': instance.employee,
  'invite_link': instance.inviteLink,
};
