// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'branch.g.dart';

@JsonSerializable()
class Branch {
  const Branch({
    required this.id,
    required this.name,
    required this.distanceKm,
  });

  factory Branch.fromJson(Map<String, Object?> json) => _$BranchFromJson(json);

  final int id;
  final String name;
  @JsonKey(name: 'distance_km')
  final num distanceKm;

  Map<String, Object?> toJson() => _$BranchToJson(this);
}
