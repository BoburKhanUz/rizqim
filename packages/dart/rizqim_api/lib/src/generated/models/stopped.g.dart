// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'stopped.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Stopped _$StoppedFromJson(Map<String, dynamic> json) => Stopped(
  products: (json['products'] as List<dynamic>)
      .map((e) => (e as num).toInt())
      .toList(),
  variants: (json['variants'] as List<dynamic>)
      .map((e) => (e as num).toInt())
      .toList(),
  modifiers: (json['modifiers'] as List<dynamic>)
      .map((e) => (e as num).toInt())
      .toList(),
);

Map<String, dynamic> _$StoppedToJson(Stopped instance) => <String, dynamic>{
  'products': instance.products,
  'variants': instance.variants,
  'modifiers': instance.modifiers,
};
