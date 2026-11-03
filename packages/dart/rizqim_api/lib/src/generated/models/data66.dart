// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data66.g.dart';

@JsonSerializable()
class Data66 {
  const Data66({required this.branchId, required this.productIds});

  factory Data66.fromJson(Map<String, Object?> json) => _$Data66FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'product_ids')
  final List<dynamic> productIds;

  Map<String, Object?> toJson() => _$Data66ToJson(this);
}
