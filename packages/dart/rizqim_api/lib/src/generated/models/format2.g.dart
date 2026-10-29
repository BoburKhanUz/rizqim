// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'format2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Format2 _$Format2FromJson(Map<String, dynamic> json) => Format2(
  profile: json['profile'] as String,
  profileLabel: json['profile_label'] as String,
  mode: json['mode'] as String,
  firstScreen: json['first_screen'] as String,
  modules: (json['modules'] as List<dynamic>).map((e) => e as String).toList(),
  locks: Locks4.fromJson(json['locks'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Format2ToJson(Format2 instance) => <String, dynamic>{
  'profile': instance.profile,
  'profile_label': instance.profileLabel,
  'mode': instance.mode,
  'first_screen': instance.firstScreen,
  'modules': instance.modules,
  'locks': instance.locks,
};
