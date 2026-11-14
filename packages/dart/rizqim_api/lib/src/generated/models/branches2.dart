// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'branches2.g.dart';

@JsonSerializable()
class Branches2 {
  const Branches2({required this.branchId, required this.isActive});

  factory Branches2.fromJson(Map<String, Object?> json) =>
      _$Branches2FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'is_active')
  final bool isActive;

  Map<String, Object?> toJson() => _$Branches2ToJson(this);
}
