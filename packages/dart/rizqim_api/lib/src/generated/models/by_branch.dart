// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by_branch.g.dart';

@JsonSerializable()
class ByBranch {
  const ByBranch({
    required this.branchId,
    required this.name,
    required this.orders,
    required this.delivered,
    required this.revenue,
  });

  factory ByBranch.fromJson(Map<String, Object?> json) =>
      _$ByBranchFromJson(json);

  @JsonKey(name: 'branch_id')
  final int? branchId;
  final String? name;
  final int orders;
  final int delivered;
  final String revenue;

  Map<String, Object?> toJson() => _$ByBranchToJson(this);
}
