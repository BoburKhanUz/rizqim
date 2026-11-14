// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'links.g.dart';

@JsonSerializable()
class Links {
  const Links({required this.productId, this.isCritical});

  factory Links.fromJson(Map<String, Object?> json) => _$LinksFromJson(json);

  @JsonKey(name: 'product_id')
  final int productId;
  @JsonKey(name: 'is_critical')
  final bool? isCritical;

  Map<String, Object?> toJson() => _$LinksToJson(this);
}
