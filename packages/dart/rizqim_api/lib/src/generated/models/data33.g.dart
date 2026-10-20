// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data33.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data33 _$Data33FromJson(Map<String, dynamic> json) => Data33(
  employee: User.fromJson(json['employee'] as Map<String, dynamic>),
  inviteLink: json['invite_link'] as String,
);

Map<String, dynamic> _$Data33ToJson(Data33 instance) => <String, dynamic>{
  'employee': instance.employee,
  'invite_link': instance.inviteLink,
};
