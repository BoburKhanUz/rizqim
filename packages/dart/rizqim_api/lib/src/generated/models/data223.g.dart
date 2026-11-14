// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data223.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data223 _$Data223FromJson(Map<String, dynamic> json) => Data223(
  branchId: (json['branch_id'] as num).toInt(),
  from: json['from'] as String,
  to: json['to'] as String,
  events: (json['events'] as List<dynamic>)
      .map((e) => Events3.fromJson(e as Map<String, dynamic>))
      .toList(),
  dishes: (json['dishes'] as List<dynamic>)
      .map((e) => Dishes4.fromJson(e as Map<String, dynamic>))
      .toList(),
  osh: Osh4.fromJson(json['osh'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data223ToJson(Data223 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'from': instance.from,
  'to': instance.to,
  'events': instance.events,
  'dishes': instance.dishes,
  'osh': instance.osh,
};
