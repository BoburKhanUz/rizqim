// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'events.g.dart';

@JsonSerializable()
class Events {
  const Events({
    required this.uid,
    required this.name,
    required this.at,
    this.storeId,
    this.productId,
    this.categoryId,
    this.props,
  });

  factory Events.fromJson(Map<String, Object?> json) => _$EventsFromJson(json);

  final String uid;
  final String name;
  final int at;
  @JsonKey(name: 'store_id')
  final int? storeId;
  @JsonKey(name: 'product_id')
  final int? productId;
  @JsonKey(name: 'category_id')
  final int? categoryId;
  final Map<String, dynamic>? props;

  Map<String, Object?> toJson() => _$EventsToJson(this);
}
