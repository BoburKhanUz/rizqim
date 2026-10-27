// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'regions.dart';
import 'districts.dart';

part 'data39.g.dart';

@JsonSerializable()
class Data39 {
  const Data39({required this.regions, required this.districts});

  factory Data39.fromJson(Map<String, Object?> json) => _$Data39FromJson(json);

  final List<Regions> regions;
  final List<Districts> districts;

  Map<String, Object?> toJson() => _$Data39ToJson(this);
}
