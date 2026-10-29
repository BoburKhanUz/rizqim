// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data108.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data108 _$Data108FromJson(Map<String, dynamic> json) => Data108(
  branchId: (json['branch_id'] as num).toInt(),
  profile: json['profile'] as String,
  profileLabel: json['profile_label'] as String,
  mode: json['mode'] as String,
  firstScreen: json['first_screen'] as String,
  modules: (json['modules'] as List<dynamic>).map((e) => e as String).toList(),
  locks: Locks.fromJson(json['locks'] as Map<String, dynamic>),
  moduleRows: (json['module_rows'] as List<dynamic>)
      .map((e) => ModuleRows.fromJson(e as Map<String, dynamic>))
      .toList(),
  profiles: (json['profiles'] as List<dynamic>)
      .map((e) => Profiles.fromJson(e as Map<String, dynamic>))
      .toList(),
  template: Template.fromJson(json['template'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data108ToJson(Data108 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'profile': instance.profile,
  'profile_label': instance.profileLabel,
  'mode': instance.mode,
  'first_screen': instance.firstScreen,
  'modules': instance.modules,
  'locks': instance.locks,
  'module_rows': instance.moduleRows,
  'profiles': instance.profiles,
  'template': instance.template,
};
