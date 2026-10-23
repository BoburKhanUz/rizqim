// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'regions2.dart';
import 'districts2.dart';

part 'data185.g.dart';

@JsonSerializable()
class Data185 {
  const Data185({required this.regions, required this.districts});

  factory Data185.fromJson(Map<String, Object?> json) =>
      _$Data185FromJson(json);

  final List<Regions2> regions;
  final List<Districts2> districts;

  Map<String, Object?> toJson() => _$Data185ToJson(this);
}
