// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'region.dart';
import 'district.dart';

part 'data60.g.dart';

@JsonSerializable()
class Data60 {
  const Data60({required this.regions, required this.districts});

  factory Data60.fromJson(Map<String, Object?> json) => _$Data60FromJson(json);

  final List<Region> regions;
  final List<District> districts;

  Map<String, Object?> toJson() => _$Data60ToJson(this);
}
