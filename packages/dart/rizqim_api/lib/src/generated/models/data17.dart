// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'regions.dart';
import 'districts.dart';

part 'data17.g.dart';

@JsonSerializable()
class Data17 {
  const Data17({required this.regions, required this.districts});

  factory Data17.fromJson(Map<String, Object?> json) => _$Data17FromJson(json);

  final List<Regions> regions;
  final List<Districts> districts;

  Map<String, Object?> toJson() => _$Data17ToJson(this);
}
